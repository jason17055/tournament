package dragonfin.tournament;

import java.awt.*;
import java.awt.event.*;
import java.util.*;
import javax.swing.*;

public class PlayParticipantsDialog extends JDialog
{
	Play play;
	SmartTable participantsTable;
	ResultSetModel participantsModel;

	static ResourceBundle strings = MainWindow.strings;

	public PlayParticipantsDialog(Window owner, Play play)
	{
		super(owner, "Edit Participants", Dialog.ModalityType.DOCUMENT_MODAL);
		this.play = play;

		makeToolbar();

		participantsModel = play.getParticipantsModel();
		participantsTable = new SmartTable(participantsModel);
		JScrollPane sp = new JScrollPane(participantsTable);
		participantsTable.setFillsViewportHeight(true);
		getContentPane().add(sp, BorderLayout.CENTER);

		JPanel buttonPane = new JPanel();
		getContentPane().add(buttonPane, BorderLayout.SOUTH);

		JButton btn1 = new JButton("OK");
		btn1.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				onOkClicked();
			}});
		buttonPane.add(btn1);

		JButton btn2 = new JButton("Cancel");
		btn2.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				dispose();
			}});
		buttonPane.add(btn2);

		pack();
		setDefaultCloseOperation(WindowConstants.DISPOSE_ON_CLOSE);
		setLocationRelativeTo(owner);
	}

	void makeToolbar()
	{
		JToolBar toolBar = new JToolBar(JToolBar.HORIZONTAL);
		toolBar.setFloatable(false);
		getContentPane().add(toolBar, BorderLayout.NORTH);

		JButton btn1 = new JButton(strings.getString("tool.add"));
		btn1.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				onAddClicked();
			}});
		toolBar.add(btn1);

		JButton btn2 = new JButton(strings.getString("tool.remove"));
		btn2.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				onRemoveClicked();
			}});
		toolBar.add(btn2);

		JButton btn3 = new JButton(strings.getString("tool.refresh"));
		btn3.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				onRefreshClicked();
			}});
		toolBar.add(btn3);
	}

	private void onOkClicked()
	{
		dispose();
	}

	private void onAddClicked()
	{
		//TODO
	}

	private void onRefreshClicked()
	{
		//TODO
	}

	private void onRemoveClicked()
	{
		//TODO
	}
}
