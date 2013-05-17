package dragonfin.tournament;

import java.awt.*;
import java.awt.event.*;
import java.io.*;
import java.text.MessageFormat;
import java.sql.*;
import java.util.*;
import javax.swing.*;
import javax.swing.filechooser.FileNameExtensionFilter;

import static dragonfin.tournament.UnexpectedExceptionDialog.showException;

public class MainWindow extends JFrame
{
	static ResourceBundle strings = ResourceBundle.getBundle("dragonfin.tournament.GuiStrings");
	static final String PRODUCT_NAME = strings.getString("PRODUCT");
	static final String EXTENSION = "tourney";

	Tournament tournament;
	File currentFile;

	static final String CARD_PLAYERS = "players";
	static final String CARD_PLAYS = "plays";

	CardLayout mainCardLayout;
	JPanel mainPane;
	String currentlyVisible = CARD_PLAYERS;

	ResultSetModel rosterModel;
	JTable rosterTable;
	JScrollPane rosterScrollPane;

	JTable playsTable;

	public MainWindow()
	{
		setTitle(PRODUCT_NAME);

		mainCardLayout = new CardLayout();
		mainPane = new JPanel(mainCardLayout);
		getContentPane().add(mainPane, BorderLayout.CENTER);

		rosterTable = new JTable();

		rosterScrollPane = new JScrollPane(rosterTable);
		rosterTable.setFillsViewportHeight(true);
		mainPane.add(rosterScrollPane, CARD_PLAYERS);

		playsTable = new JTable();
		JScrollPane sp = new JScrollPane(playsTable);
		playsTable.setFillsViewportHeight(true);
		mainPane.add(sp, CARD_PLAYS);

		makeToolbar();
		makeMenu();
		pack();
		setDefaultCloseOperation(WindowConstants.DO_NOTHING_ON_CLOSE);
		setLocationRelativeTo(null);

		addWindowListener(new WindowAdapter() {
			public void windowClosing(WindowEvent ev) {
				closeWindow();
			}
			});

		try
		{
		Tournament t = new Tournament();
		t.connectDatabase();
		t.upgradeSchema();
		t.loadMasterData();
		setTournament(null, t);

		} catch (SQLException e) {
			showException(this, e);
		}
	}

	void makeToolbar()
	{
		JToolBar toolBar = new JToolBar(JToolBar.HORIZONTAL);
		toolBar.setFloatable(false);

		JButton btn1 = new JButton(strings.getString("tool.add"));
		btn1.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				onAddClicked();
			}});
		toolBar.add(btn1);

		JButton btn2 = new JButton(strings.getString("tool.refresh"));
		btn2.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				onRefreshClicked();
			}});
		toolBar.add(btn2);

		getContentPane().add(toolBar, BorderLayout.PAGE_START);
	}

	void makeMenu()
	{
		JMenuBar menuBar = new JMenuBar();

		JMenu fileMenu = new JMenu(strings.getString("menu.file"));
		menuBar.add(fileMenu);

		JMenuItem menuItem;
	//	menuItem = new JMenuItem(strings.getString("menu.file.new"));
	//	menuItem.addActionListener(new ActionListener() {
	//		public void actionPerformed(ActionEvent ev) {
	//			onNewFileClicked();
	//		}});
	//	fileMenu.add(menuItem);
//
//		menuItem = new JMenuItem(strings.getString("menu.file.open"));
//		menuItem.addActionListener(new ActionListener() {
//			public void actionPerformed(ActionEvent ev) {
//				onOpenFileClicked();
//			}});
//		fileMenu.add(menuItem);
//
//		menuItem = new JMenuItem(strings.getString("menu.file.save"));
//		menuItem.addActionListener(new ActionListener() {
//			public void actionPerformed(ActionEvent ev) {
//				onSaveFileClicked();
//			}});
//		fileMenu.add(menuItem);
//
//		menuItem = new JMenuItem(strings.getString("menu.file.save_as"));
//		menuItem.addActionListener(new ActionListener() {
//			public void actionPerformed(ActionEvent ev) {
//				onSaveAsFileClicked();
//			}});
//		fileMenu.add(menuItem);
//
		menuItem = new JMenuItem(strings.getString("menu.file.exit"));
		menuItem.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent ev) {
				closeWindow();
			}});
		fileMenu.add(menuItem);

		JMenu viewMenu = new JMenu(strings.getString("menu.view"));
		menuBar.add(viewMenu);

		menuItem = new JMenuItem(strings.getString("menu.view.players"));
		menuItem.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				onViewPlayersClicked();
			}});
		viewMenu.add(menuItem);

		menuItem = new JMenuItem(strings.getString("menu.view.games"));
		menuItem.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				onViewGamesClicked();
			}});
		viewMenu.add(menuItem);

		setJMenuBar(menuBar);
	}

	void closeWindow()
	{
		if (!maybeSave()) {
			return;
		}

		dispose();
	}

	boolean maybeSave()
	{
		if (tournament.isDirty()) {

			int rv = JOptionPane.showConfirmDialog(
					this,
					strings.getString("main.save_query"),
					PRODUCT_NAME,
					JOptionPane.YES_NO_CANCEL_OPTION,
					JOptionPane.WARNING_MESSAGE);
			if (rv == JOptionPane.CANCEL_OPTION)
				return false;

			if (rv == JOptionPane.YES_OPTION) {
				return onSaveFileClicked();
			}
		}
		return true;
	}

	void onAddClicked()
	{
		try
		{
			if (currentlyVisible == CARD_PLAYERS) {
				tournament.addPlayer();
			}
			else if (currentlyVisible == CARD_PLAYS) {
				tournament.addPlay();
			}

			onRefreshClicked();
		}
		catch (Exception e) {
			showException(this, e);
		}
	}

	void onRefreshClicked()
	{
		try
		{

		rosterModel = tournament.getPlayersModel();
		rosterTable.setModel(rosterModel);

		ResultSetModel playsModel = tournament.getPlaysModel();
		playsTable.setModel(playsModel);

		enhanceTable(playsTable, playsModel, "PLAY");

		}
		catch (SQLException e) {
			showException(this, e);
		}
	}

	void enhanceTable(JTable playsTable, ResultSetModel playsModel, String tableName)
		throws SQLException
	{
		for (int i = 1; i < playsModel.getColumnCount(); i++) {

			String columnName = playsModel.getColumnName(i);

		String x = tournament.getColumnTypeData(tableName+"."+columnName);
		if (x != null) {
			String [] parts = x.substring(5).split(",");
			JComboBox<String> comboBox = new JComboBox<String>();
			for (String p : parts) {
				comboBox.addItem(p);
			}
			playsTable.getColumnModel().getColumn(i).setCellEditor(
				new DefaultCellEditor(comboBox)
				);
			continue;
		}

		if (tableName.equals("PLAY") && columnName.equals("PLAYERS")) {
			playsTable.getColumnModel().getColumn(i).setCellEditor(
				new PlayParticipantsEditor()
				);
		}

		} //end for
	}

	static class PlayParticipantsEditor extends AbstractCellEditor
		implements javax.swing.table.TableCellEditor
	{
		JButton button;

		PlayParticipantsEditor()
		{
			this.button = new JButton();
			this.button.addActionListener( new ActionListener() {
				public void actionPerformed(ActionEvent evt) {
					onButtonClicked();
				}});
		}

		private void onButtonClicked()
		{
			PlayParticipantsDialog dlg;
			dlg = new PlayParticipantsDialog(
				SwingUtilities.windowForComponent(button)
				);
			dlg.setVisible(true);

			fireEditingCanceled();
		}

		@Override
		public Object getCellEditorValue()
		{
			//TODO
			return null;
		}

		//implements TableCellEditor
		public Component getTableCellEditorComponent(
				JTable table, Object value,
				boolean isSelected, int row, int column)
		{
			return button;
		}
	}

	void onNewFileClicked()
	{
		if (!maybeSave()) {
			return;
		}

		setTournament(null, new Tournament());
	}

	void doSave(File file)
		throws IOException
	{
		currentFile = file;
		tournament.saveFile(file);
	}

	void onOpenFileClicked()
	{
		if (!maybeSave()) {
			return;
		}

		try
		{
			JFileChooser fc = new JFileChooser();
			FileNameExtensionFilter filter1 = new FileNameExtensionFilter(strings.getString("filetype."+EXTENSION), EXTENSION);
			fc.setFileFilter(filter1);

			int rv = fc.showOpenDialog(this);
			if (rv == JFileChooser.APPROVE_OPTION) {
				File file = fc.getSelectedFile();

				Tournament t = new Tournament();
				t.loadFile(file);

				setTournament(file, t);
			}
		}
		catch (Exception e)
		{
			JOptionPane.showMessageDialog(this, e, strings.getString("main.error_caption"),
				JOptionPane.ERROR_MESSAGE);
		}
	}

	/** @return true if file was saved, false if user canceled */
	boolean onSaveFileClicked()
	{
		if (currentFile == null) {
			return onSaveAsFileClicked();
		}

		try {
			tournament.saveFile(currentFile);
			return true;
		}
		catch (Exception e) {
			JOptionPane.showMessageDialog(this, e,
				strings.getString("main.error_caption"),
				JOptionPane.ERROR_MESSAGE);
		}
		return false;
	}

	/** @return true if file was saved, false if user canceled */
	boolean onSaveAsFileClicked()
	{
		try {

			JFileChooser fc = new JFileChooser();
			FileNameExtensionFilter filter1 = new FileNameExtensionFilter(strings.getString("filetype."+EXTENSION), EXTENSION);
			fc.setFileFilter(filter1);
			int rv = fc.showSaveDialog(this);

			if (rv == JFileChooser.APPROVE_OPTION) {
				currentFile = fc.getSelectedFile();
				if (!currentFile.getName().endsWith("."+EXTENSION)) {
					currentFile = new File(currentFile.getPath()+"."+EXTENSION);
				}
				doSave(currentFile);
				refresh();
				return true;
			}
		}
		catch (Exception e) {
			JOptionPane.showMessageDialog(this, e,
				strings.getString("main.error_caption"),
				JOptionPane.ERROR_MESSAGE);
		}
		return false;
	}

	void refresh()
	{
		if (currentFile != null) {
			String fileName = currentFile.getName();
			if (fileName.endsWith("."+EXTENSION)) {
				fileName = fileName.substring(0, fileName.length() - 1 - EXTENSION.length());
			}
			setTitle(MessageFormat.format(
					strings.getString("main.caption_named_file"),
					fileName));
		}
		else {
			setTitle(strings.getString("main.caption_unnamed_file"));
		}
	}

	public static void main(String [] args)
	{
		SwingUtilities.invokeLater( new Runnable() {
			public void run() {
				new MainWindow().setVisible(true);
			}});
	}

	public void setTournament(File file, Tournament newTournament)
	{
		assert newTournament != null;

		this.currentFile = file;
		this.tournament = newTournament;

		onRefreshClicked();
		refresh();
	}

	private void onViewPlayersClicked()
	{
		mainCardLayout.show(mainPane, CARD_PLAYERS);
		currentlyVisible = CARD_PLAYERS;
	}

	private void onViewGamesClicked()
	{
		mainCardLayout.show(mainPane, CARD_PLAYS);
		currentlyVisible = CARD_PLAYS;
	}
}
